fulldata = ""
ossz = []
f = open("palyers.txt", "r")
for i in f:
    cucc= i.rstrip("} ,\n").lstrip("{")
    
    ossz.append(cucc)
  
f = open("palyers copy.txt", "r")
for i in f:
    cucc = i.strip()
    
    fulldata += cucc
f = open("javitott.txt", "w")
print("ize")
for i in ossz:
    print("fut")
    print(i)
    if  i not in  fulldata :
        print("aktiv" )
        cucc = "}"
        f.write("{"+f"{i}, money: 0, tourCard: false"+cucc+", \n")

